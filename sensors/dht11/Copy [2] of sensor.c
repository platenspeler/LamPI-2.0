#include <wiringPi.h>  
#include <stdio.h>  
#include <stdlib.h>  
#include <stdint.h> 
 
#define MAX_TIME 85  
#define DHT11PIN 7 					// == GPIO pin 4

// ----------------------------------------------------------------
// Each message is 40 bits:
// - 8 bit: Relative Humidity, INtegral part
// - 8 bit: Relative Humidity, decimal part
// - 8 bit: Temperature; Integral part
// - 8 bit: Temperature; Decimal part
// - 8 bit: Check
//
// Protocol:
// =============
// Request:
// --------
//	Bus Free status starts by having data line HIGH
// 	Transmitter pulls down data line for at least 18 ms
//	Transmitter pull up data line leel to HIGH for 20-40 ms
//
// Confirm
// --------
// 	Receiver starts sending LOW for 80 uSec
//	Receiver Pulls to HIGH for 80 uSec
//
// Data Transfer Phase
// -------------------
// Every bit starts with a 50 uSec pulse
// Data bit is 26-28 uSec for a "0", and 70 uSec for a "1"
//
// After last it, DHT11 pulls dta line down for 50 uSec and lets loose after
//
// --------------------------------------------------------------------

int dht11_val[5]={0,0,0,0,0};  



/*
 *********************************************************************************
 * Sensor_interrupt
 *
 * This is the main interrupt routine that is called as soon as we received an
 * edge (change) on the receiver GPIO pin of the RPI. As pulses arrive once every 
 * 100uSec, we have 1/10,000 sec to do work before another edge arrives on the pin.
 *
 *
 *********************************************************************************
 */
void snesor_interrupt (void) 
{ 
	// We record the time at receiving an interrupt, and we keep track of the
	// time of the previous interrupt. The difference is duration of this edge
	// We need to handle the pulse, even if we're not doing anything with it
	// as we need to start with correct pulses.
	//
	edgeTimeStamp[0] = edgeTimeStamp[1];
    edgeTimeStamp[1] = micros();	
	duration = edgeTimeStamp[1] - edgeTimeStamp[0];		// time between 2 interrupts
	
	// As long as we process output, (we then have gathered a complete message) 
	// or the buffer is full (!), stop receiving!
	//
	if (stop_ints) {
		return;
	}
	// With an open receiver, we receive more short pulses than long pulses.
	// Specially shorter than 100 uSec means reubbish in most cases.
	// We therefore filter out too short or too long pulses. This method works as a low pass filter.
	// If the duration is shorter than the normalized pulse_lenght of
	// low_pass (80) uSec, then we must discard the message. There is much noise on the 433MHz
	// band and interrupt time must be kept short! We keep a 30% margin!
	// So for protocols with shorter timing we should lower low_pass parameter,
	// but this is probably not necessary
	//
	if ( (duration < (int)(low_pass))					// Low pass filter
		|| (duration > 15000) )							
	{					
		return;
	}
		
	// Record this time
	//
	pulse_array[p_index] = duration;
	p_index++;
		
	// Index contains the NEXT position to store a timing position in
	// If the nexy position is going to be out of bounds, reset position to begin of array
	//
	if (p_index >= MAXDATASIZE) {
		p_index = 0;
	}
	

	return;
}

/*
 *********************************************************************************
 * dht11_read_val
 *
 * 
 *
 *
 *********************************************************************************
 */

  
void dht11_read_val()  
{  
  uint8_t lststate=HIGH;  
  uint8_t counter=0;  
  uint8_t j=0,i;  
  for(i=0;i<5;i++)  
     dht11_val[i]=0;  

  // Send the request pulse
   
  pinMode(DHT11PIN,OUTPUT);  
  digitalWrite(DHT11PIN,LOW);  
  delay(18);  
  digitalWrite(DHT11PIN,HIGH);  
  delayMicroseconds(40); 
  
  // Switch to input mode 
  
  pinMode(DHT11PIN,INPUT);
  
  // Receive bits
  
  for(i=0;i<MAX_TIME;i++)  
  {  
    counter=0;  
    while(digitalRead(DHT11PIN)==lststate){  
      counter++;  
      delayMicroseconds(1);  
      if(counter==255)  
        break;  
    } 
	
	 
    lststate=digitalRead(DHT11PIN);  
    if(counter==255)  
       break;  
	   
    // top 3 transistions are ignored  
    if((i>=4)&&(i%2==0))
	{  
      dht11_val[j/8]<<=1;  
      if(counter>16)  
        dht11_val[j/8]|=1;  
      j++;  
    }  
  } 
  
  if (j<40) {
  	fprintf(stderr,"Not 40 bits\n");
	exit (EXIT_FAILURE);
	
  }
  
  // verify cheksum and print the verified data  
  if((j>=40)&&(dht11_val[4]==((dht11_val[0]+dht11_val[1]+dht11_val[2]+dht11_val[3])& 0xFF)))  
  {  
    printf("humidity-relative:%d.%d|temperature-celsius:%d.%d\n",dht11_val[0],dht11_val[1],dht11_val[2],dht11_val[3]);  
  }  
  else {  
   printf("Invalid Data\n");
   exit(EXIT_FAILURE);
  }
} 


/* ********************************************************************
 * MAIN PROGRAM
 *
 *
 *
 * ********************************************************************	*/  
int main(void)  
{  
//  printf("Interfacing Temperature and Humidity Sensor (DHT11) With Raspberry Pi\n");  
  if(wiringPiSetup()==-1) exit(EXIT_FAILURE);  
  while(1)  
  {  
     dht11_read_val();  
     delay(3000);  
  }
    exit(EXIT_SUCCESS); 
}  
