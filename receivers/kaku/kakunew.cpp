
/*
* kaku.c:
* Simple program to control klik-aan-klik-uit power devices
*/

#include <wiringPi.h>

#include <stdio.h>
#include <stdlib.h>
#include <stdint.h>
#include <string.h>
#include <unistd.h>
#include "NewRemoteTransmitter.cpp"

#define _periodusec 375
#define _repeats 3
#define on True
#define off False

//typedef enum {False=0, True} boolean;
//typedef unsigned char byte;

 /*
*/
 
 static void display_usage(const char *cmd)
 {
	fprintf(stderr, "Usage: %s groupid deviceid on|off|dimvalue\n\n", cmd);
	fprintf(stderr,	"\nExamples: ");
	fprintf(stderr,	"\n%s 100 1 7  \n", cmd);
	fprintf(stderr,	"\n%s 101 1 off\n", cmd);
 }

 int main(int argc, char **argv)
 {
	int pin = 15;
	int dim = 10;				// Just a dimming value
	long switch_group = 100;
	int switch_dev = 1;
	int n=0;
	int m=0;

	if (argc < 2){
	display_usage(argv[0]);
		return 1;
	}	

   	if (wiringPiSetup () == -1)
		exit (1) ;

	pinMode (pin, OUTPUT) ;
	switch_group = atoi(argv[1]);
	switch_dev= atoi(argv[2]);

	NewRemoteTransmitter transmitter(switch_group, 15, 260, 3);

	fprintf(stderr,"args: %d, %d\n",switch_group,switch_dev);

	if ( ! strcmp(argv[3],"on")) {
		transmitter.sendUnit(switch_dev,true);
	}
	else if (! strcmp(argv[3],"off")) {
		transmitter.sendUnit(switch_dev,false);
	}
	else {
		dim=atoi(argv[3]);
		transmitter.sendDim(switch_dev,dim);
	}

	return 0;
 }
